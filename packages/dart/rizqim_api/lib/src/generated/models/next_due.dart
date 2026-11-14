// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'next_due.g.dart';

@JsonSerializable()
class NextDue {
  const NextDue({required this.date, required this.amount, required this.kind});

  factory NextDue.fromJson(Map<String, Object?> json) =>
      _$NextDueFromJson(json);

  final String date;
  final String amount;
  final String kind;

  Map<String, Object?> toJson() => _$NextDueToJson(this);
}
