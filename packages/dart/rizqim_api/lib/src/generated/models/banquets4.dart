// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken8.dart';
import 'refunded9.dart';

part 'banquets4.g.dart';

@JsonSerializable()
class Banquets4 {
  const Banquets4({required this.taken, required this.refunded});

  factory Banquets4.fromJson(Map<String, Object?> json) =>
      _$Banquets4FromJson(json);

  final Taken8 taken;
  final Refunded9 refunded;

  Map<String, Object?> toJson() => _$Banquets4ToJson(this);
}
