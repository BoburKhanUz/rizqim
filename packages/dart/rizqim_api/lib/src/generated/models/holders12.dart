// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff33.dart';

part 'holders12.g.dart';

@JsonSerializable()
class Holders12 {
  const Holders12({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders12.fromJson(Map<String, Object?> json) =>
      _$Holders12FromJson(json);

  final String mode;
  final Staff33? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders12ToJson(this);
}
