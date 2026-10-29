// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'lines6.g.dart';

@JsonSerializable()
class Lines6 {
  const Lines6({
    required this.staffId,
    required this.name,
    required this.role,
    required this.basis,
    required this.amount,
  });

  factory Lines6.fromJson(Map<String, Object?> json) => _$Lines6FromJson(json);

  @JsonKey(name: 'staff_id')
  final int? staffId;
  final String? name;
  final String? role;
  final String basis;
  final String amount;

  Map<String, Object?> toJson() => _$Lines6ToJson(this);
}
