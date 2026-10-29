// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff18.dart';

part 'holders7.g.dart';

@JsonSerializable()
class Holders7 {
  const Holders7({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders7.fromJson(Map<String, Object?> json) =>
      _$Holders7FromJson(json);

  final String mode;
  final Staff18? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders7ToJson(this);
}
