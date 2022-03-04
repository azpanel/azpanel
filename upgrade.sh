#/bin/bash

dir=$(pwd)

checkGit()
{
    if [[ ! -d "${dir}/.git" ]];then
        echo "Update only supports deploying with git."
        exit
    fi

    if [[ ! -e "/usr/bin/git" ]];then
        echo "You need to install the git command first. Try running [yum -y install git] or [apt-get -y install git]."
        exit
    fi
}

checkoutConfirm()
{
    read -p "The update will execute the [git checkout .] command, which will lose all your custom modifications, are you sure you want to continue? [y/n]:" reply
    if [[ ${reply} == 'n' ]];then
        echo "In fact, if you have experience with git, you can update it with your custom changes saved by using the [git stash] command."
        exit
    fi

    git checkout .
}

pullUpdate()
{
    git fetch
    git merge

    echo "Syncing to the latest version is complete."
}

main()
{
    checkGit
    checkoutConfirm
    pullUpdate
}

main