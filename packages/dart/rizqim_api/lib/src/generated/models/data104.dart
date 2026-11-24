// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data104.g.dart';

@JsonSerializable()
class Data104 {
  const Data104({
    required this.uuid,
    required this.name,
    required this.branchId,
    required this.hasApiKey,
    required this.apiKeyLast4,
    required this.apiKeySetAt,
    required this.ready,
    required this.message,
  });

  factory Data104.fromJson(Map<String, Object?> json) =>
      _$Data104FromJson(json);

  final String uuid;
  final String? name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'has_api_key')
  final bool hasApiKey;
  @JsonKey(name: 'api_key_last4')
  final String? apiKeyLast4;
  @JsonKey(name: 'api_key_set_at')
  final String? apiKeySetAt;
  final bool ready;
  final String? message;

  Map<String, Object?> toJson() => _$Data104ToJson(this);
}
