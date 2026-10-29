// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'transactions6.g.dart';

@JsonSerializable()
class Transactions6 {
  const Transactions6({required this.amount, this.rrn});

  factory Transactions6.fromJson(Map<String, Object?> json) =>
      _$Transactions6FromJson(json);

  final String? rrn;
  final String amount;

  Map<String, Object?> toJson() => _$Transactions6ToJson(this);
}
