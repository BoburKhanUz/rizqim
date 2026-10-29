// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff9.dart';

part 'holders3.g.dart';

@JsonSerializable()
class Holders3 {
  const Holders3({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders3.fromJson(Map<String, Object?> json) =>
      _$Holders3FromJson(json);

  final String mode;
  final Staff9? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders3ToJson(this);
}
