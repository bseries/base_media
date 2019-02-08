#!/bin/bash -x
#
# Copyright 2013 David Persson. All rights reserved.
# Copyright 2016 Atelier Disko. All rights reserved.
#
# Use of this source code is governed by a BSD-style
# license that can be found in the LICENSE file.

set -o nounset
set -o errexit

# Must be executed from the library root. Will operate on and modify the
# *current* files in tree. Be sure to operate on a copy.
[[ ! -d config ]] && echo "error: not invoked from library root" && exit 1

for f in $(ls resources/g11n/po/*/LC_MESSAGES/*.po); do
	msgfmt -o ${f/.po/.mo} --verbose $f
done

cat << EOF > .browserslistrc
last 2 Chrome versions
last 2 Firefox versions
last 2 Safari versions
EOF

# Babel cli can be installed locally, but presets are always search locally.
cat << EOF > package.json
{
	"devDependencies": {
		"@babel/preset-env": "^7.0.0"
	}
}
EOF
npm install --save-dev

# Babelify in-place for full current ESx compatiblity.
cat << EOF > .babelrc
{
	"presets": [
		[
			"@babel/preset-env", {
				"debug": true
			}
		]
	]
}
EOF
babel assets/js -d assets/js

for f in $(find assets/js -type f -name *.js); do
	uglifyjs --compress --mangle -o $f.min -- $f && mv $f.min $f
done

for f in $(ls assets/*.css); do
	css-nextgen $f > $f.tmp && mv $f.tmp $f
	cleancss --skip-rebase $f -o $f.min && mv $f.min $f
done

rm .browserslistrc .babelrc
