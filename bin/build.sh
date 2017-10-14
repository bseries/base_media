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

# Babelify in-place for ES2015 compatiblity. Once we do not want to support IE11
# and iOS Safari <= 9.3 anymore we can safely remove this line or use babel
# to continously upgrade supported ECMAScript versions.
babel assets/js \
	-d assets/js \
	--presets babel-preset-es2015 \
	--ignore underscore.js,require.js,require,jquery.js,modernizr.js

for f in $(find assets/js -type f -name *.js); do
	uglifyjs --compress --mangle -o $f.min -- $f && mv $f.min $f
done

