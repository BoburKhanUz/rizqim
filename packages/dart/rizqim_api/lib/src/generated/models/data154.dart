// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'hub3.dart';
import 'candidates3.dart';

part 'data154.g.dart';

@JsonSerializable()
class Data154 {
  const Data154({
    required this.branchId,
    required this.keyId,
    required this.rotatedAt,
    required this.port,
    required this.hub,
    required this.candidates,
  });

  factory Data154.fromJson(Map<String, Object?> json) =>
      _$Data154FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'key_id')
  final int keyId;
  @JsonKey(name: 'rotated_at')
  final String? rotatedAt;
  final int port;
  final Hub3? hub;
  final List<Candidates3> candidates;

  Map<String, Object?> toJson() => _$Data154ToJson(this);
}
