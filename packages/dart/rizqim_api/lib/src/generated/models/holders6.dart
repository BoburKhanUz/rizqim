// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff16.dart';

part 'holders6.g.dart';

@JsonSerializable()
class Holders6 {
  const Holders6({
    required this.mode,
    required this.staff,
    required this.amount,
  });

  factory Holders6.fromJson(Map<String, Object?> json) =>
      _$Holders6FromJson(json);

  final String mode;
  final Staff16? staff;
  final String amount;

  Map<String, Object?> toJson() => _$Holders6ToJson(this);
}
