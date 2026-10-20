// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_auth_phone_send_code_request_body.g.dart';

@JsonSerializable()
class V1AuthPhoneSendCodeRequestBody {
  const V1AuthPhoneSendCodeRequestBody({required this.phone});

  factory V1AuthPhoneSendCodeRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AuthPhoneSendCodeRequestBodyFromJson(json);

  final String phone;

  Map<String, Object?> toJson() => _$V1AuthPhoneSendCodeRequestBodyToJson(this);
}
