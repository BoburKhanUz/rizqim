// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff20.dart';

part 'holders8.g.dart';

@JsonSerializable()
class Holders8 {
  const Holders8({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders8.fromJson(Map<String, Object?> json) =>
      _$Holders8FromJson(json);

  final String mode;
  final Staff20? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders8ToJson(this);
}
