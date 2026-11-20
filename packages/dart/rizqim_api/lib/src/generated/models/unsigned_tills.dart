// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'unsigned_tills.g.dart';

@JsonSerializable()
class UnsignedTills {
  const UnsignedTills({
    required this.uuid,
    required this.name,
    required this.branchId,
    required this.hasKey,
    required this.lastSigStatus,
  });

  factory UnsignedTills.fromJson(Map<String, Object?> json) =>
      _$UnsignedTillsFromJson(json);

  final String uuid;
  final String name;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'has_key')
  final bool hasKey;
  @JsonKey(name: 'last_sig_status')
  final String? lastSigStatus;

  Map<String, Object?> toJson() => _$UnsignedTillsToJson(this);
}
