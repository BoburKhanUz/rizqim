// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device2.dart';

part 'v1_auth_phone_verify_request_body.g.dart';

@JsonSerializable()
class V1AuthPhoneVerifyRequestBody {
  const V1AuthPhoneVerifyRequestBody({
    required this.phone,
    required this.code,
    this.device,
  });

  factory V1AuthPhoneVerifyRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AuthPhoneVerifyRequestBodyFromJson(json);

  final String phone;
  final String code;
  final Device2? device;

  Map<String, Object?> toJson() => _$V1AuthPhoneVerifyRequestBodyToJson(this);
}
