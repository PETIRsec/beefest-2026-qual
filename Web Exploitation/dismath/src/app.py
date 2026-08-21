from flask import Flask, render_template, request, send_file, jsonify
import subprocess
import tempfile
import os
import shutil

app = Flask(__name__)

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/compile', methods=['POST'])
def compile_tex():
    code = request.json.get('code', '')
    tmpdir = tempfile.mkdtemp()
    texpath = os.path.join(tmpdir, 'main.tex')

    with open(texpath, 'w') as f:
        f.write(code)

    try:
        result = subprocess.run(
            ['pdflatex', '-shell-escape', '-interaction=nonstopmode',
             '-output-directory', tmpdir, texpath],
            capture_output=True,
            text=True,
            timeout=10
        )

        pdfpath = os.path.join(tmpdir, 'main.pdf')

        if os.path.exists(pdfpath):
            return send_file(pdfpath, mimetype='application/pdf')

        return jsonify({'log': result.stdout + '\n' + result.stderr}), 400

    except subprocess.TimeoutExpired:
        return jsonify({'log': 'Compilation timed out.'}), 400
    except Exception as e:
        return jsonify({'log': str(e)}), 500
    finally:
        shutil.rmtree(tmpdir, ignore_errors=True)


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=1337)
