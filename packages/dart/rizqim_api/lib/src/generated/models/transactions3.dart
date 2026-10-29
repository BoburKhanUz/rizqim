// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'transactions3.g.dart';

@JsonSerializable()
class Transactions3 {
  const Transactions3({required this.amount, this.rrn});

  factory Transactions3.fromJson(Map<String, Object?> json) =>
      _$Transactions3FromJson(json);

  final String? rrn;
  final String amount;

  Map<String, Object?> toJson() => _$Transactions3ToJson(this);
}
