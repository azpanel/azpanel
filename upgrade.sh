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

    if [[ ${reply} != 'y' ]];then
        echo -e "${green_color}In fact, if you have experience with git, you can update it with your custom changes saved by using the [git stash] command.${color_end}"
        exit
    fi

    git checkout .
}

pullUpdate()
{
    git fetch
    git merge origin/master

    echo -e "${green_color}Update to the latest version is complete.${color_end}"
}

modifyVersion()
{
    #version=$(cat ${dir}/version)
    #if [[ $version == '' ]];then
        #version=$(git log --format="%ct" | wc -l)
    #fi

    version=$(git log --format="%ct" | wc -l)

    big_v='1'
    medium_v='1'
    small_v=$(expr ${version} - 105)
    hash=$(git log -1 --format="%h")

    # tvl = tpl version line
    user_tvl=$(cat -n ${dir}/app/view/user/header.html | grep '<span>v.' | awk '{print $1}')
    admin_tvl=$(cat -n ${dir}/app/view/admin/header.html | grep '<span>v.' | awk '{print $1}')

    sed -i "${user_tvl}c\        <span>v.${big_v}.${medium_v}.${small_v} ${hash}</span>" ${dir}/app/view/user/header.html
    sed -i "${admin_tvl}c\        <span>v.${big_v}.${medium_v}.${small_v} ${hash}</span>" ${dir}/app/view/admin/header.html
}

main()
{
    checkGit
    checkoutConfirm
    pullUpdate
    modifyVersion
}

main
