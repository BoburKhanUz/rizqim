// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_admin_auth_confirm_request_body.g.dart';

@JsonSerializable()
class V1AdminAuthConfirmRequestBody {
  const V1AdminAuthConfirmRequestBody({required this.code});

  factory V1AdminAuthConfirmRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminAuthConfirmRequestBodyFromJson(json);

  final String code;

  Map<String, Object?> toJson() => _$V1AdminAuthConfirmRequestBodyToJson(this);
}
