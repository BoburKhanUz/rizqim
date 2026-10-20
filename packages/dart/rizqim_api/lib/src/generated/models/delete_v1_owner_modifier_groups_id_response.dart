// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'delete_v1_owner_modifier_groups_id_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerModifierGroupsIdResponse {
  const DeleteV1OwnerModifierGroupsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerModifierGroupsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerModifierGroupsIdResponseFromJson(json);

  final bool success;
  final String message;
  final dynamic data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerModifierGroupsIdResponseToJson(this);
}
