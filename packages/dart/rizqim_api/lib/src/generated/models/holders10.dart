// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff27.dart';

part 'holders10.g.dart';

@JsonSerializable()
class Holders10 {
  const Holders10({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders10.fromJson(Map<String, Object?> json) =>
      _$Holders10FromJson(json);

  final String mode;
  final Staff27? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders10ToJson(this);
}
