// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'taken6.g.dart';

@JsonSerializable()
class Taken6 {
  const Taken6({
    required this.count,
    required this.amount,
    required this.cash,
    required this.card,
    required this.qr,
  });

  factory Taken6.fromJson(Map<String, Object?> json) => _$Taken6FromJson(json);

  final int count;
  final String amount;
  final String cash;
  final String card;
  final String qr;

  Map<String, Object?> toJson() => _$Taken6ToJson(this);
}
