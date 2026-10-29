// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'write_offs2.g.dart';

@JsonSerializable()
class WriteOffs2 {
  const WriteOffs2({
    required this.reason,
    required this.label,
    required this.count,
    required this.quantity,
  });

  factory WriteOffs2.fromJson(Map<String, Object?> json) =>
      _$WriteOffs2FromJson(json);

  final String reason;
  final String label;
  final String count;
  final num quantity;

  Map<String, Object?> toJson() => _$WriteOffs2ToJson(this);
}
