// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'taken.g.dart';

@JsonSerializable()
class Taken {
  const Taken({required this.count, required this.amount, required this.cash});

  factory Taken.fromJson(Map<String, Object?> json) => _$TakenFromJson(json);

  final int count;
  final String amount;
  final String cash;

  Map<String, Object?> toJson() => _$TakenToJson(this);
}
