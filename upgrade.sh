#/bin/bash

dir=$(pwd)

green_color='\033[32m'
yellow_color='\033[33m'
red_color='\033[31m'
color_end='\033[0m'

checkGit()
{
    if [[ ! -d "${dir}/.git" ]];then
        echo -e "${red_color}Update only supports deploying with git.${color_end}"
        exit
    fi

    if [[ ! -e "/usr/bin/git" ]];then
        echo -e "${red_color}You need to install the git command first. Try running [yum -y install git] or [apt-get -y install git].${color_end}"
        exit
    fi
}

checkoutConfirm()
{
    echo -ne "${yellow_color}The update will execute the [git checkout .] command, which will lose all your custom modifications, are you sure you want to continue? [y/n]:${color_end}"
    read reply
    
    if [[ ${reply} == 'n' ]];then
        echo -e "${green_color}In fact, if you have experience with git, you can update it with your custom changes saved by using the [git stash] command.${color_end}"
        exit
    fi

    git checkout .
}

pullUpdate()
{
    git fetch
    git merge

    echo -e "${green_color}Update to the latest version is complete.${color_end}"
}

main()
{
    checkGit
    checkoutConfirm
    pullUpdate
}

main