"""Behavior contracts for the parent boundary; no credentials or cloud requests."""
import copy
import importlib.util
import io
import json
from pathlib import Path
import ssl
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[3]
SPEC = importlib.util.spec_from_file_location('launcher', ROOT / 'deploy/cloudrun/browser-ci/launcher.py')
u = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(u)
SOURCE = 'a' * 40
CONTROL = 'b' * 40
PARENT = '11111111-1111-1111-1111-111111111111'
CHILD = '22222222-2222-2222-2222-222222222222'


def metadata(status='SUCCESS'):
    return {'id': CHILD, 'projectId': u.PROJECT, 'serviceAccount': u.CHILD_SA, 'status': status,
            'steps': [{'id': step.get('id'), 'status': 'SUCCESS'}
                      for step in u.child_recipe(SOURCE, CONTROL)['steps']],
            'substitutions': {'_SHA': SOURCE, '_CONTROL_SHA': CONTROL, '_BUCKET': u.BUCKET}}


class Response:
    def __init__(self, data=b'payload', status=200, length=None):
        self.status = status
        self.data = io.BytesIO(data)
        self.length = str(len(data)) if length is None else length
        self.reads = []

    def read(self, count):
        self.reads.append(count)
        return self.data.read(count)

    def getheader(self, name):
        return None if self.length == 'absent' else self.length


class Connection:
    def __init__(self, response):
        self.response = response
        self.requests = []
        self.closed = False

    def request(self, *args, **kwargs):
        self.requests.append((args, kwargs))

    def getresponse(self):
        return self.response

    def close(self):
        self.closed = True


class LauncherContracts(unittest.TestCase):
    def test_exact_inputs_fail_before_creation_and_private_values_are_omitted(self):
        with patch.object(u, 'create_child') as create, patch.object(u, 'publish_receipt') as publish:
            for source, control, parent, bucket, verifier in [
                ('private-invalid-input', CONTROL, PARENT, u.BUCKET, u.VERIFY_BUCKET),
                (SOURCE, 'main', PARENT, u.BUCKET, u.VERIFY_BUCKET),
                (SOURCE, CONTROL, 'invalid', u.BUCKET, u.VERIFY_BUCKET),
                (SOURCE, CONTROL, PARENT, 'wrong', u.VERIFY_BUCKET),
                (SOURCE, CONTROL, PARENT, u.BUCKET, 'wrong')]:
                record = u.verify(source, control, parent, bucket, verifier)
                self.assertFalse(record['passed'])
                self.assertEqual('input-validation', record['stage'])
                self.assertNotIn('private-invalid-input', json.dumps(record))
            create.assert_not_called()
            publish.assert_not_called()

    def test_recipe_overrides_identity_and_exact_substitutions(self):
        with tempfile.TemporaryDirectory() as scratch:
            path = Path(scratch)
            (path / 'child-cloudbuild.json').write_text(json.dumps({'serviceAccount': u.PARENT_SA,
                'substitutions': {'_SHA': 'other', '_UNSAFE': 'unexpected'}, 'steps': [{'id': 'test'}]}))
            with patch.object(u, 'HERE', path):
                result = u.child_recipe(SOURCE, CONTROL)
        self.assertEqual(u.CHILD_SA, result['serviceAccount'])
        self.assertEqual({'_SHA': SOURCE, '_CONTROL_SHA': CONTROL, '_BUCKET': u.BUCKET}, result['substitutions'])
        self.assertEqual([{'id': 'test'}], result['steps'])

    def test_metadata_mismatch_or_unknown_status_is_rejected(self):
        variants = []
        for key, value in [('id', PARENT), ('projectId', 'wrong'), ('serviceAccount', u.PARENT_SA),
                           ('status', 'unknown')]:
            variant = metadata();variant[key] = value;variants.append(variant)
        for key, value in [('_SHA', CONTROL), ('_CONTROL_SHA', SOURCE), ('_BUCKET', u.VERIFY_BUCKET),
                           ('_EXTRA', 'unexpected')]:
            variant = metadata();variant['substitutions'][key] = value;variants.append(variant)
        for variant in variants:
            with patch.object(u, 'api_json', return_value=variant):
                with self.assertRaises(u.VerificationError):
                    u.child_metadata(CHILD, SOURCE, CONTROL)
        with patch.object(u, 'api_json', return_value=metadata()) as api:
            self.assertEqual('SUCCESS', u.child_metadata(CHILD, SOURCE, CONTROL)['status'])
            self.assertIn('/'+CHILD+'?', api.call_args.args[1])

    def test_provider_failure_cannot_be_overridden_by_child_artifacts(self):
        with tempfile.TemporaryDirectory() as scratch, patch.object(u, 'create_child', return_value=CHILD), \
                patch.object(u, 'await_child', return_value=metadata('FAILURE')), \
                patch.object(u, 'download_artifact') as download, patch.object(u, 'enforce') as enforce, \
                patch.object(u, 'publish_receipt') as publish:
            result = u.verify(SOURCE, CONTROL, PARENT, directory=Path(scratch)/'verification')
            self.assertFalse(result['passed'])
            self.assertTrue(result['metadata_verified'])
            self.assertEqual('FAILURE', result['child_provider_status'])
            self.assertEqual('child-provider-failure', result['stage'])
            download.assert_not_called();enforce.assert_not_called();publish.assert_called_once()

    def test_overall_success_with_allowed_step_failure_is_rejected_before_artifacts(self):
        build = metadata()
        build['steps'][1].update({'status': 'FAILURE', 'exitCode': 7, 'allowFailure': True})
        with tempfile.TemporaryDirectory() as scratch, patch.object(u, 'create_child', return_value=CHILD), \
                patch.object(u, 'await_child', return_value=build), \
                patch.object(u, 'download_artifact') as download, patch.object(u, 'enforce') as enforce, \
                patch.object(u, 'publish_receipt') as publish:
            result = u.verify(SOURCE, CONTROL, PARENT, directory=Path(scratch)/'verification')
        self.assertFalse(result['passed'])
        self.assertEqual('SUCCESS', result['child_provider_status'])
        self.assertFalse(result['provider_steps_verified'])
        self.assertEqual('child-provider-steps', result['stage'])
        download.assert_not_called();enforce.assert_not_called();publish.assert_called_once()

    def test_every_expected_provider_step_requires_matching_id_success_and_integer_zero(self):
        recipe = u.child_recipe(SOURCE, CONTROL)
        variants = [dict(metadata(), steps=[]), dict(metadata(), steps=None)]
        build = metadata();build['steps'].pop();variants.append(build)
        build = metadata();build['steps'].append({'id': 'unexpected', 'status': 'SUCCESS'});variants.append(build)
        for change in [{'id': 'wrong'}, {'status': 'FAILURE'}, {'status': None},
                       {'exitCode': 1}, {'exitCode': True}, {'exitCode': False}, {'exitCode': '0'}, {'exitCode': None}]:
            build = metadata();build['steps'][1].update(change);variants.append(build)
        build = metadata();del build['steps'][1]['status'];variants.append(build)
        build = metadata();del build['steps'][1]['id'];variants.append(build)
        for build in variants:
            with self.assertRaises(u.VerificationError):u.verify_child_steps(build, recipe)
        for exit_code in ['absent', 0]:
            build = metadata()
            if exit_code != 'absent':
                for step in build['steps']:step['exitCode'] = exit_code
            self.assertIsNone(u.verify_child_steps(build, recipe))
        # Operator-owned tiny probe recipes may omit IDs; matched counts and statuses still apply.
        tiny_recipe = {'steps': [{'name': 'trusted-toy'}, {'name': 'trusted-toy'}]}
        tiny_build = {'steps': [{'status': 'SUCCESS'}, {'status': 'SUCCESS', 'exitCode': 0}]}
        self.assertIsNone(u.verify_child_steps(tiny_build, tiny_recipe))

    def test_invalid_archive_is_rejected_by_real_parent_enforcer(self):
        def download(name, destination, limit):
            if name.endswith('/result.json'):
                data = json.dumps({'passed': True, 'exit_code': 0, 'last_stage': 'complete',
                                   'source_sha': SOURCE, 'control_sha': CONTROL}).encode()
            elif name.endswith('/runner-exit.json'):
                data = b'{"docker_exit_code":0}'
            else:
                data = b'not-a-tar-archive'
            Path(destination).write_bytes(data)
            return {'name': name, 'bytes': len(data), 'sha256': 'synthetic'}
        with tempfile.TemporaryDirectory() as scratch, patch.object(u, 'create_child', return_value=CHILD), \
                patch.object(u, 'await_child', return_value=metadata()), \
                patch.object(u, 'download_artifact', side_effect=download), \
                patch.object(u, 'publish_receipt'):
            result = u.verify(SOURCE, CONTROL, PARENT, directory=Path(scratch)/'verification')
        self.assertFalse(result['passed'])
        self.assertFalse(result['enforcement_passed'])
        self.assertEqual('enforcement-failed', result['stage'])
        self.assertEqual('SUCCESS', result['child_provider_status'])

    def test_success_requires_all_named_bounded_artifacts_and_parent_enforcement(self):
        with tempfile.TemporaryDirectory() as scratch, patch.object(u, 'create_child', return_value=CHILD), \
                patch.object(u, 'await_child', return_value=metadata()), \
                patch.object(u, 'download_artifact', return_value={'bytes': 1}) as download, \
                patch.object(u, 'enforce', return_value=True) as enforce, patch.object(u, 'publish_receipt'):
            result = u.verify(SOURCE, CONTROL, PARENT, directory=Path(scratch)/'verification')
        self.assertTrue(result['passed'])
        self.assertTrue(result['metadata_verified'])
        self.assertTrue(result['provider_steps_verified'])
        self.assertTrue(result['enforcement_passed'])
        self.assertEqual('complete', result['stage'])
        self.assertEqual([SOURCE+'/'+CHILD+'/'+n for n in ['result.json','runner-exit.json','evidence.tar.gz']],
                         [call.args[0] for call in download.call_args_list])
        self.assertEqual([u.JSON_LIMIT,u.JSON_LIMIT,u.ARCHIVE_LIMIT],
                         [call.args[2] for call in download.call_args_list])
        self.assertEqual((SOURCE,CONTROL), enforce.call_args.args[-2:])
        self.assertIn('branch-reported', result['trust_limit'])

    def test_verified_tls_download_is_bounded_and_never_follows_redirects(self):
        for response, expected in [(Response(b'hello'), True), (Response(b'',status=301), False),
                                   (Response(b'oversized',length='99'),False),
                                   (Response(b'123456',length='absent'),False),
                                   (Response(b'xx',length='3'),False)]:
            conn = Connection(response)
            with tempfile.TemporaryDirectory() as scratch, \
                    patch.object(u.http.client, 'HTTPSConnection', return_value=conn) as factory, \
                    patch.object(u, 'access_token', return_value='private-token-marker'):
                target = Path(scratch)/'artifact'
                if expected:
                    receipt = u.download_artifact(SOURCE+'/'+CHILD+'/result.json',target,5)
                    self.assertEqual(5,receipt['bytes']);self.assertEqual(b'hello',target.read_bytes())
                else:
                    with self.assertRaises(u.VerificationError):
                        u.download_artifact(SOURCE+'/'+CHILD+'/result.json',target,5)
                self.assertEqual('storage.googleapis.com',factory.call_args.args[0])
                context=factory.call_args.kwargs['context']
                self.assertTrue(context.check_hostname);self.assertEqual(ssl.CERT_REQUIRED,context.verify_mode)
                self.assertEqual(1,len(conn.requests));self.assertEqual('GET',conn.requests[0][0][0])
                self.assertNotIn('private-token-marker',conn.requests[0][0][1])
                self.assertTrue(conn.closed)
                if response.status==301:self.assertEqual([],response.reads)
                if target.exists():self.assertLessEqual(target.stat().st_size,5)

    def test_build_api_uses_fresh_protected_tokens_and_suppresses_failure_body(self):
        connections=[Connection(Response(b'{"status":"WORKING"}')),Connection(Response(b'{"status":"SUCCESS"}'))]
        with patch.object(u.http.client,'HTTPSConnection',side_effect=connections) as factory, \
                patch.object(u,'access_token',side_effect=['private-first','private-second']) as tokens:
            u.api_json('GET',u.API_ROOT+'/'+CHILD);u.api_json('GET',u.API_ROOT+'/'+CHILD)
        self.assertEqual(2,tokens.call_count)
        for conn,token in zip(connections,['private-first','private-second']):
            self.assertEqual('Bearer '+token,conn.requests[0][1]['headers']['Authorization'])
            self.assertNotIn(token,conn.requests[0][0][1]);self.assertTrue(conn.closed)
        response=Response(b'private-error-body',status=403);conn=Connection(response)
        with patch.object(u.http.client,'HTTPSConnection',return_value=conn),patch.object(u,'access_token',return_value='private'):
            with self.assertRaises(u.VerificationError) as error:u.api_json('POST',u.API_ROOT,{})
        self.assertEqual('build-api',str(error.exception));self.assertEqual([],response.reads)
        self.assertTrue(factory.call_args.kwargs['context'].check_hostname)

    def test_creation_requires_real_provider_child_id_and_terminal_polling(self):
        with patch.object(u,'api_json',return_value={'metadata':{'build':{'id':CHILD}}}) as api:
            self.assertEqual(CHILD,u.create_child({'serviceAccount':u.CHILD_SA}))
            self.assertEqual('POST',api.call_args.args[0]);self.assertIn('projectId=',api.call_args.args[1])
        for operation in [{},{'metadata':{'build':{'id':'invalid'}}}]:
            with patch.object(u,'api_json',return_value=operation):
                with self.assertRaises(u.VerificationError):u.create_child({})
        with patch.object(u,'child_metadata',side_effect=[metadata('WORKING'),metadata('SUCCESS')]) as get, \
                patch.object(u.time,'sleep') as sleep:
            self.assertEqual('SUCCESS',u.await_child(CHILD,SOURCE,CONTROL)['status'])
            self.assertEqual(2,get.call_count);sleep.assert_called_once_with(10)

    def test_only_absolute_reviewed_enforcer_executes_with_isolated_python_and_timeout(self):
        with patch.object(u.subprocess,'run',return_value=subprocess.CompletedProcess([],0)) as run:
            self.assertTrue(u.enforce(Path('/safe/results'),Path('/safe/outer.json'),SOURCE,CONTROL))
        argv=run.call_args.args[0]
        self.assertEqual(['-I',str(u.HERE/'enforce.py')],argv[1:3])
        self.assertEqual(180,run.call_args.kwargs['timeout'])
        self.assertTrue(run.call_args.kwargs['capture_output'])
        self.assertNotIn('evidence.tar.gz',argv)
        with tempfile.TemporaryDirectory() as scratch, patch.object(u,'create_child',side_effect=RuntimeError('private-api-token-error')), \
                patch.object(u,'publish_receipt') as publish:
            receipt=u.verify(SOURCE,CONTROL,PARENT,directory=Path(scratch)/'verification')
        self.assertFalse(receipt['passed']);self.assertEqual('verification-error',receipt['stage'])
        self.assertNotIn('private-api-token-error',json.dumps(receipt));publish.assert_called_once()


if __name__ == '__main__':
    unittest.main()
