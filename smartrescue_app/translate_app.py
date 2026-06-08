import os
import re

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Need to remove const from Text('...') and wrap string in AppTranslator.t(context, '...')
    # Also remove const from const Row( children: [ Text(...) ] ) ? This is harder.
    # It's better to just do a naive regex and then fix compilation errors.
    
    # Actually, a safer regex:
    # Find Text('Some text') or Text("Some text")
    # Replace with Text(AppTranslator.t(context, 'Some text'))
    # And remove any 'const ' before 'Text('
    
    # 1. remove const Text(
    content = re.sub(r'const\s+Text\(', r'Text(', content)
    
    # 2. wrap strings in Text
    # This regex looks for Text( 'string'
    # We must be careful not to match variables.
    def replace_text(match):
        quote = match.group(1)
        text = match.group(2)
        rest = match.group(3)
        # Don't translate if it has variables ($)
        if '$' in text:
            return match.group(0)
        return f"Text(AppTranslator.t(context, {quote}{text}{quote}){rest}"

    content = re.sub(r"Text\(\s*(['\"])(.*?)\1(.*?)", replace_text, content)
    
    # 3. Add import if not present
    if 'AppTranslator' in content and 'translator.dart' not in content:
        # find last import
        import_idx = content.rfind("import '")
        if import_idx != -1:
            end_idx = content.find(";", import_idx)
            content = content[:end_idx+1] + "\nimport '../../utils/translator.dart';" + content[end_idx+1:]
            
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

if __name__ == '__main__':
    files_to_process = [
        r'c:\xampp\htdocs\SmartRescueApp\smartrescue_app\lib\views\user\user_community_rescue_screen.dart',
        r'c:\xampp\htdocs\SmartRescueApp\smartrescue_app\lib\views\user\user_settings_screen.dart',
        r'c:\xampp\htdocs\SmartRescueApp\smartrescue_app\lib\views\user\user_home_screen.dart',
        r'c:\xampp\htdocs\SmartRescueApp\smartrescue_app\lib\views\user\user_shell.dart',
    ]
    for f in files_to_process:
        if os.path.exists(f):
            process_file(f)
            print(f"Processed {f}")
