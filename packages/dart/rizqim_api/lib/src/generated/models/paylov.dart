// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'paylov.g.dart';

@JsonSerializable()
class Paylov {
  const Paylov({
    this.enabled,
    this.merchantId,
    this.consumerKey,
    this.consumerSecret,
    this.username,
    this.password,
    this.callbackLogin,
    this.callbackPassword,
  });

  factory Paylov.fromJson(Map<String, Object?> json) => _$PaylovFromJson(json);

  final bool? enabled;
  @JsonKey(name: 'merchant_id')
  final String? merchantId;
  @JsonKey(name: 'consumer_key')
  final String? consumerKey;
  @JsonKey(name: 'consumer_secret')
  final String? consumerSecret;
  final String? username;
  final String? password;
  @JsonKey(name: 'callback_login')
  final String? callbackLogin;
  @JsonKey(name: 'callback_password')
  final String? callbackPassword;

  Map<String, Object?> toJson() => _$PaylovToJson(this);
}
