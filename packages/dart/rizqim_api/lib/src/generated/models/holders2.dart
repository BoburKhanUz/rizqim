// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff5.dart';

part 'holders2.g.dart';

@JsonSerializable()
class Holders2 {
  const Holders2({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders2.fromJson(Map<String, Object?> json) =>
      _$Holders2FromJson(json);

  final String mode;
  final Staff5? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders2ToJson(this);
}
