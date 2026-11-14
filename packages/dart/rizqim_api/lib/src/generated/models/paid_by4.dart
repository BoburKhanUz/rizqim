// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'paid_by4.g.dart';

@JsonSerializable()
class PaidBy4 {
  const PaidBy4({required this.id, required this.name});

  factory PaidBy4.fromJson(Map<String, Object?> json) =>
      _$PaidBy4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$PaidBy4ToJson(this);
}
