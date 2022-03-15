#/bin/bash

mysqldump -d azpanel --skip-comments | sed 's/AUTO_INCREMENT=[0-9]*\s*//g' > database/azure.sql
echo "The database structure file has been exported."
