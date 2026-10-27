// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_admin_auth_invite_accept_request_body.g.dart';

@JsonSerializable()
class V1AdminAuthInviteAcceptRequestBody {
  const V1AdminAuthInviteAcceptRequestBody({
    required this.token,
    required this.password,
  });

  factory V1AdminAuthInviteAcceptRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1AdminAuthInviteAcceptRequestBodyFromJson(json);

  final String token;
  final String password;

  Map<String, Object?> toJson() =>
      _$V1AdminAuthInviteAcceptRequestBodyToJson(this);
}
