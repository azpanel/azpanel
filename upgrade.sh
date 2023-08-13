#!/bin/bash

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

    number_of_files_before_update=$(ls database/migrations | wc -l | awk '{print $1}')
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
    #git fetch
    #git merge origin/master
    current_composer_json_md5=$(md5sum composer.json | awk '{print $1}')
    git pull

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
    medium_v='2'
    small_v=$(expr ${version} - 211)
    hash=$(git log -1 --format="%h")

    # tvl = tpl version line
    #user_tvl=$(cat -n ${dir}/app/view/user/header.html | grep '<span>v.' | awk '{print $1}')
    #admin_tvl=$(cat -n ${dir}/app/view/admin/header.html | grep '<span>v.' | awk '{print $1}')

    #sed -i "${user_tvl}c\        <span>v.${big_v}.${medium_v}.${small_v} ${hash}</span>" ${dir}/app/view/user/header.html
    #sed -i "${admin_tvl}c\        <span>v.${big_v}.${medium_v}.${small_v} ${hash}</span>" ${dir}/app/view/admin/header.html
    php think tools --action setVersion --newVersion "${big_v}.${medium_v}.${small_v} ${hash}"
}

databaseMigration()
{
    number_of_files_after_update=$(ls database/migrations | wc -l | awk '{print $1}')
    if [[ "${number_of_files_before_update}" != "${number_of_files_after_update}" ]];then
        php think migrate:run
    fi
}

judgment()
{
    new_composer_json_md5=$(md5sum composer.json | awk '{print $1}')
    if [[ "${current_composer_json_md5}" != "${new_composer_json_md5}" ]];then
        if [[ -e "/usr/local/bin/composer" ]];then
            composer update
        else
            echo -e "${yellow_color}composer.json 文件内容有变动, 但没有找到 composer 命令.${color_end}"
            echo -e "${yellow_color}为确保正常运行, 请稍后在网站根目录下手动执行 composer update${color_end}"
        fi
    fi
}

main()
{
    checkGit
    checkoutConfirm
    pullUpdate
    judgment
    databaseMigration
    modifyVersion
}

main
