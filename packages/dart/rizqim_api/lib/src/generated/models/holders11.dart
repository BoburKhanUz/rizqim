// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff31.dart';

part 'holders11.g.dart';

@JsonSerializable()
class Holders11 {
  const Holders11({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders11.fromJson(Map<String, Object?> json) =>
      _$Holders11FromJson(json);

  final String mode;
  final Staff31? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders11ToJson(this);
}
