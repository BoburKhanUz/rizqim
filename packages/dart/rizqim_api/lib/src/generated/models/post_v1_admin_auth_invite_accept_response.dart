// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data8.dart';

part 'post_v1_admin_auth_invite_accept_response.g.dart';

@JsonSerializable()
class PostV1AdminAuthInviteAcceptResponse {
  const PostV1AdminAuthInviteAcceptResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuthInviteAcceptResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1AdminAuthInviteAcceptResponseFromJson(json);

  final bool success;
  final String message;
  final Data8 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminAuthInviteAcceptResponseToJson(this);
}
