// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_phone_verify_code_request_body.g.dart';

@JsonSerializable()
class V1PhoneVerifyCodeRequestBody {
  const V1PhoneVerifyCodeRequestBody({required this.phone, required this.code});

  factory V1PhoneVerifyCodeRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PhoneVerifyCodeRequestBodyFromJson(json);

  final String phone;
  final String code;

  Map<String, Object?> toJson() => _$V1PhoneVerifyCodeRequestBodyToJson(this);
}
