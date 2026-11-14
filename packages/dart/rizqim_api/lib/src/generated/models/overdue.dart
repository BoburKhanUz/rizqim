// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'overdue.g.dart';

@JsonSerializable()
class Overdue {
  const Overdue({required this.count, required this.amount});

  factory Overdue.fromJson(Map<String, Object?> json) =>
      _$OverdueFromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$OverdueToJson(this);
}
