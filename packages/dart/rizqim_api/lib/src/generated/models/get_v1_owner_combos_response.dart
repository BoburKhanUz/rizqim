// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data59.dart';

part 'get_v1_owner_combos_response.g.dart';

@JsonSerializable()
class GetV1OwnerCombosResponse {
  const GetV1OwnerCombosResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerCombosResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerCombosResponseFromJson(json);

  final bool success;
  final String message;
  final Data59 data;

  Map<String, Object?> toJson() => _$GetV1OwnerCombosResponseToJson(this);
}
