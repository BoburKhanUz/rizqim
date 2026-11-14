// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'paid_by3.g.dart';

@JsonSerializable()
class PaidBy3 {
  const PaidBy3({required this.id, required this.name});

  factory PaidBy3.fromJson(Map<String, Object?> json) =>
      _$PaidBy3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$PaidBy3ToJson(this);
}
