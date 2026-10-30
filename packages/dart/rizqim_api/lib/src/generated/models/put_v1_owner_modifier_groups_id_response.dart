// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data102.dart';

part 'put_v1_owner_modifier_groups_id_response.g.dart';

@JsonSerializable()
class PutV1OwnerModifierGroupsIdResponse {
  const PutV1OwnerModifierGroupsIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerModifierGroupsIdResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerModifierGroupsIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data102 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerModifierGroupsIdResponseToJson(this);
}
