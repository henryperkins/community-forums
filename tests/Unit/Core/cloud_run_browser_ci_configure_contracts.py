"""Configuration convergence must retire only the browser entry point."""
import importlib.util
import json
from pathlib import Path
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[3]
SPEC = importlib.util.spec_from_file_location('ci_configure', ROOT / 'deploy/cloudrun/browser-ci/configure.py')
u = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(u)


class ConfigurationContracts(unittest.TestCase):
    def test_apply_disables_only_browser_routing_and_keeps_permissions_restricted(self):
        project, region = u.PROJECT, u.REGION
        url = f'https://cloudbuild.googleapis.com/v1/projects/{project}/locations/{region}/triggers/'
        hooks = [
            {'id': 10, 'config': {'url': url + 'retroboards-main:webhook?key=private-key'}},
            {'id': 20, 'config': {'url': url + 'retroboards-browser-evidence:webhook?key=private-key'}},
            {'id': 30, 'config': {'url': url.replace(region, 'other-region') + 'retroboards-browser-evidence:webhook'}},
        ]
        imports, mutations, commands = [], [], []
        def gc(*args, optional=False):
            commands.append(args)
            if args[:4] == ('builds','triggers','describe','retroboards-main'):
                return json.dumps({'id':'production-id','webhookConfig':{'secret':'private-secret'}})
            if args[:4] == ('builds','triggers','describe',u.TRIGGER):
                return json.dumps({'id':'browser-id','name':u.TRIGGER,'disabled':False,
                                   'webhookConfig':{'secret':'private-secret'}})
            if args[:3] == ('builds','triggers','import'):
                imports.append(json.loads(Path(next(x.split('=',1)[1] for x in args if x.startswith('--source='))).read_text()))
            return '{}'
        def invoke(args, optional=False):
            if args[-1] == '--paginate': return json.dumps(hooks)
            mutations.append((args, json.loads(Path(args[-1]).read_text())))
            return '{}'
        with patch.object(u,'gc',side_effect=gc),patch.object(u,'invoke',side_effect=invoke):
            result=u.configure(True)
        self.assertEqual(1,len(imports));self.assertTrue(imports[0]['disabled'])
        self.assertEqual('false', imports[0]['filter'])
        self.assertEqual(u.TRIGGER,imports[0]['name'])
        self.assertEqual([20],result['disabled_github_hooks'])
        self.assertEqual('repos/'+u.REPO+'/hooks/20',mutations[0][0][2])
        self.assertEqual({'active':False},mutations[0][1])
        self.assertFalse(any('--permissions=cloudbuild.builds.create' in ' '.join(args) for args in commands))
        self.assertTrue(all('--condition=None' in args for args in commands if args[:2] == ('projects','add-iam-policy-binding')))
        self.assertNotIn('private-',json.dumps(result))
        self.assertNotIn('parent', result)
        self.assertFalse(any(args[:2] == ('iam','roles') for args in commands))


if __name__ == '__main__':
    unittest.main()
