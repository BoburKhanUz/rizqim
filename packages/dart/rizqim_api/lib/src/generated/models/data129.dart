// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'hub.dart';
import 'candidates.dart';

part 'data129.g.dart';

@JsonSerializable()
class Data129 {
  const Data129({
    required this.branchId,
    required this.keyId,
    required this.rotatedAt,
    required this.port,
    required this.hub,
    required this.candidates,
  });

  factory Data129.fromJson(Map<String, Object?> json) =>
      _$Data129FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'key_id')
  final int keyId;
  @JsonKey(name: 'rotated_at')
  final String? rotatedAt;
  final int port;
  final Hub? hub;
  final List<Candidates> candidates;

  Map<String, Object?> toJson() => _$Data129ToJson(this);
}
