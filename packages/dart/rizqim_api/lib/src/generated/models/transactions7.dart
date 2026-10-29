// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'transactions7.g.dart';

@JsonSerializable()
class Transactions7 {
  const Transactions7({
    required this.rrn,
    required this.amount,
    required this.matched,
  });

  factory Transactions7.fromJson(Map<String, Object?> json) =>
      _$Transactions7FromJson(json);

  final String? rrn;
  final String amount;
  final bool matched;

  Map<String, Object?> toJson() => _$Transactions7ToJson(this);
}
