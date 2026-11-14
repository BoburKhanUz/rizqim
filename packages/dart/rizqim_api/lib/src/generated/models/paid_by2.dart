// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'paid_by2.g.dart';

@JsonSerializable()
class PaidBy2 {
  const PaidBy2({required this.id, required this.name});

  factory PaidBy2.fromJson(Map<String, Object?> json) =>
      _$PaidBy2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$PaidBy2ToJson(this);
}
