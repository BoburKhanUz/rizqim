// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'hub2.dart';
import 'candidates2.dart';

part 'data130.g.dart';

@JsonSerializable()
class Data130 {
  const Data130({
    required this.branchId,
    required this.keyId,
    required this.rotatedAt,
    required this.port,
    required this.hub,
    required this.candidates,
  });

  factory Data130.fromJson(Map<String, Object?> json) =>
      _$Data130FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'key_id')
  final int keyId;
  @JsonKey(name: 'rotated_at')
  final String? rotatedAt;
  final int port;
  final Hub2? hub;
  final List<Candidates2> candidates;

  Map<String, Object?> toJson() => _$Data130ToJson(this);
}
