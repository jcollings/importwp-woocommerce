#!/usr/bin/env bash

if [ $# -lt 1 ]; then
	echo "usage: $0 <version>"
	exit 1
fi

TAG=${1}

sed -i -r "s/^\*\*Version: (.+)\*\*$/\*\*Version: $TAG\*\*/g" README.md
sed -i -r "s/^ \* Version: (.+) $/ \* Version: $TAG /g" woocommerce.php