// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_phone_send_code_request_body.g.dart';

@JsonSerializable()
class V1PhoneSendCodeRequestBody {
  const V1PhoneSendCodeRequestBody({required this.phone});

  factory V1PhoneSendCodeRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PhoneSendCodeRequestBodyFromJson(json);

  final String phone;

  Map<String, Object?> toJson() => _$V1PhoneSendCodeRequestBodyToJson(this);
}
