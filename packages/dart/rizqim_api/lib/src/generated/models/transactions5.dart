// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'transactions5.g.dart';

@JsonSerializable()
class Transactions5 {
  const Transactions5({
    required this.rrn,
    required this.amount,
    required this.matched,
  });

  factory Transactions5.fromJson(Map<String, Object?> json) =>
      _$Transactions5FromJson(json);

  final String? rrn;
  final String amount;
  final bool matched;

  Map<String, Object?> toJson() => _$Transactions5ToJson(this);
}
