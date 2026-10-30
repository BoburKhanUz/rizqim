// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff11.dart';

part 'holders4.g.dart';

@JsonSerializable()
class Holders4 {
  const Holders4({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders4.fromJson(Map<String, Object?> json) =>
      _$Holders4FromJson(json);

  final String mode;
  final Staff11? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders4ToJson(this);
}
