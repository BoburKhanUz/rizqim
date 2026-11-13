// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sold5.g.dart';

@JsonSerializable()
class Sold5 {
  const Sold5({
    required this.count,
    required this.amount,
    required this.cash,
    required this.card,
    required this.qr,
  });

  factory Sold5.fromJson(Map<String, Object?> json) => _$Sold5FromJson(json);

  final int count;
  final String amount;
  final String cash;
  final String card;
  final String qr;

  Map<String, Object?> toJson() => _$Sold5ToJson(this);
}
