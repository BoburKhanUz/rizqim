// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sold6.g.dart';

@JsonSerializable()
class Sold6 {
  const Sold6({
    required this.count,
    required this.amount,
    required this.cash,
    required this.card,
    required this.qr,
  });

  factory Sold6.fromJson(Map<String, Object?> json) => _$Sold6FromJson(json);

  final int count;
  final String amount;
  final String cash;
  final String card;
  final String qr;

  Map<String, Object?> toJson() => _$Sold6ToJson(this);
}
