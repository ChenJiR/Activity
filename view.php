<!DOCTYPE html>
<html>
<head>
    <title>
        线上征文活动页
    </title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">
</head>
<body>
<div id="app">
    <el-container>
        <el-header>
            <el-page-header v-if="is_login" title="退出" @back="logout()" :content="phone">
            </el-page-header>
        </el-header>

        <el-main>

            <el-row>
                <el-col :span="12">
                    <el-form v-if="!is_login" ref="form" :model="form" label-width="80px">
                        <el-form-item label="手机号">
                            <el-input v-model="form.phone"></el-input>
                        </el-form-item>
                        <el-form-item label="验证码">
                            <el-input v-model="form.code"></el-input>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="sendVerifyCode">发送验证码</el-button>
                            <el-button @click="signUp">立即报名</el-button>
                        </el-form-item>
                    </el-form>

                    <el-form v-if="is_login && !text" ref="form" :model="form" label-width="80px">
                        <el-form-item label="征文内容">
                            <el-input type="textarea" :autosize="{ minRows: 14, maxRows: 24}"
                                      v-model="form.text"></el-input>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="submitText">提交征文</el-button>
                        </el-form-item>
                    </el-form>

                    <el-button v-if="is_login && text && !is_lottery" @click="lottery">抽奖</el-button>

                    <span v-if="is_lottery">您今天已经参加过了，请明日再来</span>

                </el-col>
            </el-row>
        </el-main>

    </el-container>
</div>
</body>
<!-- import Vue before Element-->
<script src="https://cdn.staticfile.org/vue/2.2.2/vue.min.js"></script>
<!-- import Axios-->
<script src="https://cdn.staticfile.org/axios/0.18.0/axios.min.js"></script>
<!-- import JavaScript-->
<script src="https://unpkg.com/element-ui/lib/index.js"></script>
<script>
    new Vue({
        el: '#app',
        data() {
            const auth = localStorage.getItem("Auth");
            return {
                is_login: !!auth,
                form: {phone: "", code: "", text: ""},
                phone: "",
                text: "",
                is_lottery: false,
                axios: axios.create({
                    timeout: 1000,
                    headers: auth ? {'Auth': auth} : {}
                })
            }
        },
        methods: {
            getUserInfo() {
                var _this = this;
                this.axios.get('?r=getUserInfo')
                    .then(function (response) {
                        switch (response.data.code) {
                            case 0:
                                _this.phone = response.data.data.phone;
                                _this.text = response.data.data.text;
                                _this.is_lottery = response.data.data.is_lottery;
                                break;
                            case -2:
                            case -1:
                            default:
                                _this.logout();
                                break;
                        }
                    })
                    .catch(function (error) {
                        _this.logout();
                    });
            },
            sendVerifyCode() {
                if (!this.form.phone) {
                    this.$alert('手机号不能为空');
                    return;
                }
                var _this = this;
                this.axios.get('?r=sendVerifyCode&phone=' + this.form.phone)
                    .then(function (response) {
                        if (response.data.code === 0) {
                            _this.form.code = response.data.data;
                            _this.$notify({title: '成功', message: '发送成功', type: 'success'});
                        } else {
                            _this.$notify.error({title: '发送失败', message: response.data.msg});
                        }
                    })
                    .catch(function (error) {
                        _this.$notify.error({title: '发送失败'});
                    });
            },
            signUp() {
                if (!this.form.phone || !this.form.code) {
                    this.$alert('手机号及验证码不能为空');
                    return;
                }
                var _this = this;
                this.axios.get('?r=signUp&phone=' + this.form.phone + "&code=" + this.form.code)
                    .then(function (res) {
                        if (res.data.code === 0) {
                            _this.$notify({title: '成功', message: '报名成功', type: 'success'});
                            localStorage.setItem("Auth", res.data.data.token);
                            _this.axios = axios.create({
                                timeout: 1000,
                                headers: {'Auth': res.data.data.token}
                            });
                            _this.is_login = true;
                            _this.phone = res.data.data.phone;
                            _this.text = res.data.data.text;
                            _this.is_lottery = res.data.data.is_lottery;
                        } else {
                            _this.$notify.error({title: '发送失败', message: res.data.msg});
                        }
                    })
                    .catch(function (error) {
                        _this.$notify.error({title: '发送失败'});
                    });
            },
            logout() {
                localStorage.removeItem("Auth");
                this.axios = axios.create({timeout: 1000, headers: {}})
                this.phone = "";
                this.text = "";
                this.is_login = false;
                this.is_lottery = false;
            },
            submitText() {
                if (!this.form.text) {
                    this.$alert('征文内容不能为空');
                    return;
                }
                var _this = this;
                this.axios.post('?r=submitText', this.form)
                    .then(function (res) {
                        switch (res.data.code) {
                            case 0:
                                _this.text = _this.form.text;
                                _this.$notify({title: '成功', message: '提交成功', type: 'success'});
                                break;
                            case -2:
                                _this.$notify.error({title: '登录失效', message: res.data.msg});
                                _this.logout();
                                break;
                            case -1:
                            default:
                                _this.$notify.error({title: '提交失败', message: res.data.msg});
                                break;
                        }
                    })
                    .catch(function (error) {
                        _this.$notify.error({title: '提交失败'});
                    });
            },
            lottery() {
                var _this = this;
                this.axios.get('?r=lottery')
                    .then(function (res) {
                        switch (res.data.code) {
                            case 0:
                                _this.$alert('恭喜你中奖了，奖品为' + res.data.data, '恭喜');
                                _this.is_lottery = true;
                                break;
                            case 1:
                                _this.$alert(res.data.msg);
                                _this.is_lottery = true;
                                break;
                            case -2:
                                _this.$notify.error({title: '登录失效', message: res.data.msg});
                                _this.logout();
                                break;
                            case -1:
                            default:
                                _this.$notify.error({title: '提交失败', message: res.data.msg});
                                break;
                        }
                    })
                    .catch(function (error) {
                        _this.$notify.error({title: '抽奖失败，请稍后重试'});
                    });
            }
        },
        mounted() {
            localStorage.getItem("Auth") && this.getUserInfo();
        }
    })
</script>
</html>