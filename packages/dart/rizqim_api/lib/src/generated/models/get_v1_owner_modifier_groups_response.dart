// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data38.dart';

part 'get_v1_owner_modifier_groups_response.g.dart';

@JsonSerializable()
class GetV1OwnerModifierGroupsResponse {
  const GetV1OwnerModifierGroupsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerModifierGroupsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerModifierGroupsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data38> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerModifierGroupsResponseToJson(this);
}
