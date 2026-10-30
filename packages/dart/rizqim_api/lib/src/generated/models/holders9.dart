// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff25.dart';

part 'holders9.g.dart';

@JsonSerializable()
class Holders9 {
  const Holders9({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders9.fromJson(Map<String, Object?> json) =>
      _$Holders9FromJson(json);

  final String mode;
  final Staff25? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders9ToJson(this);
}
