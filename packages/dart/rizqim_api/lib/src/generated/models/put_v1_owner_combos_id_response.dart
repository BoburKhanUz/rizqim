// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data61.dart';

part 'put_v1_owner_combos_id_response.g.dart';

@JsonSerializable()
class PutV1OwnerCombosIdResponse {
  const PutV1OwnerCombosIdResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerCombosIdResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerCombosIdResponseFromJson(json);

  final bool success;
  final String message;
  final Data61 data;

  Map<String, Object?> toJson() => _$PutV1OwnerCombosIdResponseToJson(this);
}
