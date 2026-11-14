// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'next_due3.g.dart';

@JsonSerializable()
class NextDue3 {
  const NextDue3({
    required this.date,
    required this.amount,
    required this.kind,
  });

  factory NextDue3.fromJson(Map<String, Object?> json) =>
      _$NextDue3FromJson(json);

  final String date;
  final String amount;
  final String kind;

  Map<String, Object?> toJson() => _$NextDue3ToJson(this);
}
