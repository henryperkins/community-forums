"""Operator coordination contracts; no cloud requests or credentials."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[3]
SOURCE, CONTROL = 'a' * 40, 'b' * 40
CHILD = '11111111-1111-1111-1111-111111111111'
VERIFIER = '22222222-2222-2222-2222-222222222222'


class SubmissionContracts(unittest.TestCase):
    def setUp(self):
        path = ROOT / 'deploy/cloudrun/browser-ci/submit.py'
        self.assertTrue(path.exists(), 'Explicit coordinator is not implemented')
        spec = importlib.util.spec_from_file_location('ci_submit', path)
        self.u = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(self.u)
        original = self.u.read_recipe
        self.recipe_patch = patch.object(self.u, 'read_recipe', side_effect=lambda control, name: json.loads(
            (ROOT / 'deploy/cloudrun/browser-ci' / name).read_text()))
        self.recipe_patch.start()
        self.addCleanup(self.recipe_patch.stop)

    def test_verifier_is_submitted_only_after_child_finishes_and_resume_reuses_ids(self):
        events = []
        def create(kind, source, control, child=None):
            events.append(('create', kind))
            if kind == 'verifier': self.assertEqual(('wait', 'child'), events[-2])
            return CHILD if kind == 'child' else VERIFIER
        def wait(build, kind, source, control, child=None):
            events.append(('wait', kind))
            return 'SUCCESS'
        with tempfile.TemporaryDirectory() as scratch, patch.object(self.u, 'find_existing', return_value=None), \
                patch.object(self.u, 'create_build', side_effect=create), \
                patch.object(self.u, 'wait_for_build', side_effect=wait), \
                patch.object(self.u, 'read_receipt', return_value={'passed': True}), \
                patch.object(self.u, 'progress'):
            state = Path(scratch) / 'state.json'
            result = self.u.coordinate(SOURCE, CONTROL, state)
            self.assertTrue(result['passed'])
            self.assertEqual([('create','child'),('wait','child'),('create','verifier'),('wait','verifier')], events)
            events.clear()
            self.u.coordinate(SOURCE, CONTROL, state)
            self.assertEqual([('wait','child'),('wait','verifier')], events)
            self.assertEqual(0o600, state.stat().st_mode & 0o777)

    def test_interruption_during_receipt_read_leaves_state_unaccepted(self):
        with tempfile.TemporaryDirectory() as scratch, patch.object(self.u, 'find_existing', return_value=None), \
                patch.object(self.u, 'create_build', side_effect=[CHILD, VERIFIER]), \
                patch.object(self.u, 'wait_for_build', return_value='SUCCESS'), \
                patch.object(self.u, 'read_receipt', side_effect=KeyboardInterrupt), \
                patch.object(self.u, 'progress'):
            state=Path(scratch)/'state.json'
            with self.assertRaises(KeyboardInterrupt): self.u.coordinate(SOURCE, CONTROL, state)
            self.assertFalse(json.loads(state.read_text())['passed'])

    def test_failed_child_and_verifier_cannot_produce_pass(self):
        with tempfile.TemporaryDirectory() as scratch, patch.object(self.u, 'find_existing', return_value=None), \
                patch.object(self.u, 'create_build', side_effect=[CHILD, VERIFIER]), \
                patch.object(self.u, 'wait_for_build', return_value='FAILURE'), \
                patch.object(self.u, 'read_receipt', return_value={'passed': True}), \
                patch.object(self.u, 'progress'):
            result = self.u.coordinate(SOURCE, CONTROL, Path(scratch)/'state.json')
            self.assertFalse(result['passed'])
            self.assertEqual('FAILURE', result['child_status'])

    def test_invalid_or_mismatched_resume_fails_before_cloud_calls(self):
        with tempfile.TemporaryDirectory() as scratch, patch.object(self.u, 'find_existing') as find, \
                patch.object(self.u, 'create_build') as create:
            state=Path(scratch)/'state.json'
            for source in ['main', '$(private)', 'a'*39]:
                with self.assertRaises(ValueError): self.u.coordinate(source, CONTROL, state)
            state.write_text(json.dumps({'source_sha':CONTROL,'control_sha':CONTROL}))
            with self.assertRaises(ValueError): self.u.coordinate(SOURCE,CONTROL,state)
            find.assert_not_called();create.assert_not_called()

    def test_existing_selection_requires_exact_identity_recipe_and_supported_status(self):
        recipe=self.u.recipe('child',SOURCE,CONTROL)
        valid={'id':CHILD,'serviceAccount':recipe['serviceAccount'],
               'substitutions':recipe['substitutions'],'status':'WORKING'}
        for changed in [{'serviceAccount':'other'},{'status':'FAILURE'},
                        {'substitutions':dict(recipe['substitutions'],_EXTRA='other')}]:
            with patch.object(self.u,'cloud_json',return_value=[dict(valid,**changed), valid]):
                self.assertEqual(CHILD,self.u.find_existing('child',SOURCE,CONTROL))

    def test_fresh_run_ignores_old_success_but_reuses_active_work(self):
        expected=self.u.recipe('child',SOURCE,CONTROL)
        complete={'id':CHILD,'serviceAccount':expected['serviceAccount'],
                  'substitutions':expected['substitutions'],'status':'SUCCESS'}
        active=dict(complete,id=VERIFIER,status='QUEUED')
        with patch.object(self.u,'cloud_json',return_value=[complete,active]):
            self.assertEqual(VERIFIER,self.u.find_existing('child',SOURCE,CONTROL,reuse_success=False))
        with patch.object(self.u,'cloud_json',return_value=[complete]):
            self.assertIsNone(self.u.find_existing('child',SOURCE,CONTROL,reuse_success=False))

    def test_receipt_requires_exact_identity_and_every_acceptance_flag(self):
        valid={'source_sha':SOURCE,'control_sha':CONTROL,'child_build_id':CHILD,
               'parent_build_id':VERIFIER,'passed':True,'metadata_verified':True,
               'provider_steps_verified':True,'enforcement_passed':True}
        with patch.object(self.u,'cloud_json',return_value=valid):
            self.assertEqual(valid,self.u.read_receipt(SOURCE,CONTROL,CHILD,VERIFIER))
        for changed in [{'passed':False},{'enforcement_passed':1},{'child_build_id':VERIFIER}]:
            with patch.object(self.u,'cloud_json',return_value=dict(valid,**changed)):
                with self.assertRaises(ValueError): self.u.read_receipt(SOURCE,CONTROL,CHILD,VERIFIER)


if __name__ == '__main__':
    unittest.main()
