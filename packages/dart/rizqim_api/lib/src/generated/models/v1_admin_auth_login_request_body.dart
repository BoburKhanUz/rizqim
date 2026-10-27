// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_admin_auth_login_request_body.g.dart';

@JsonSerializable()
class V1AdminAuthLoginRequestBody {
  const V1AdminAuthLoginRequestBody({
    required this.email,
    required this.password,
  });

  factory V1AdminAuthLoginRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1AdminAuthLoginRequestBodyFromJson(json);

  final String email;
  final String password;

  Map<String, Object?> toJson() => _$V1AdminAuthLoginRequestBodyToJson(this);
}
