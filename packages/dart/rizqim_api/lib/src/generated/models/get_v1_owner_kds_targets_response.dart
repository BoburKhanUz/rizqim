// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data105.dart';

part 'get_v1_owner_kds_targets_response.g.dart';

@JsonSerializable()
class GetV1OwnerKdsTargetsResponse {
  const GetV1OwnerKdsTargetsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerKdsTargetsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerKdsTargetsResponseFromJson(json);

  final bool success;
  final String message;
  final Data105 data;

  Map<String, Object?> toJson() => _$GetV1OwnerKdsTargetsResponseToJson(this);
}
