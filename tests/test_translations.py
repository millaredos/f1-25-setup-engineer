"""PO failures must stop packaging before a broken catalog reaches an installation."""
from pathlib import Path
import sys
import tempfile
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / 'bin'))
import translations
import polib


class TranslationTests(unittest.TestCase):
    def catalog(self, **values):
        po = polib.POFile()
        po.metadata = {'Language': 'es', 'Content-Type': 'text/plain; charset=UTF-8', 'Plural-Forms': 'nplurals=2; plural=(n != 1);'}
        po.append(polib.POEntry(msgid='Hola {name}', msgstr='Hola {name}', **values))
        return po

    def compile(self, po):
        with tempfile.TemporaryDirectory(prefix='f125-po-') as directory:
            path = Path(directory) / 'es.po'
            po.save(str(path))
            return translations.compile_catalog(path)

    def test_generated_catalog_is_current_and_complete(self):
        translations.build(check=True)

    def test_quotes_and_multiline_text(self):
        po = self.catalog()
        po[0].msgstr = 'Un "texto" de {name}\ncon \\ y una comilla: \' fin'
        content = self.compile(po)
        self.assertIn("\\' fin", content)
        self.assertIn('con \\\\', content)

    def test_placeholder_loss_rejected(self):
        po = self.catalog(); po[0].msgstr = 'Hola'
        with self.assertRaisesRegex(ValueError, 'placeholders'):
            self.compile(po)

    def test_fuzzy_or_empty_rejected(self):
        for mode in ['fuzzy', 'empty']:
            po = self.catalog()
            if mode == 'fuzzy': po[0].flags = ['fuzzy']
            else: po[0].msgstr = ''
            with self.subTest(mode=mode), self.assertRaises(ValueError):
                self.compile(po)

    def test_duplicate_or_malformed_rejected(self):
        with tempfile.TemporaryDirectory(prefix='f125-po-') as directory:
            path = Path(directory) / 'es.po'
            for text in ['msgid "x"\nmsgstr "y"\n\nmsgid "x"\nmsgstr "z"', 'msgid "x"\nmsgstr "y"\nbroken statement']:
                path.write_text(text, encoding='utf-8')
                with self.assertRaises((ValueError, OSError)):
                    translations.compile_catalog(path)

    def test_new_plural_rule_requires_runtime_support(self):
        po = self.catalog(); po.metadata['Plural-Forms'] = 'nplurals=3; plural=n;'
        with self.assertRaisesRegex(ValueError, 'plural rule'):
            self.compile(po)


if __name__ == '__main__':
    unittest.main()
