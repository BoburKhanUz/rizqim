// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data66.dart';

part 'post_v1_owner_employees_invite_response.g.dart';

@JsonSerializable()
class PostV1OwnerEmployeesInviteResponse {
  const PostV1OwnerEmployeesInviteResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerEmployeesInviteResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerEmployeesInviteResponseFromJson(json);

  final bool success;
  final String message;
  final Data66 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerEmployeesInviteResponseToJson(this);
}
