// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken10.dart';
import 'refunded11.dart';

part 'banquets5.g.dart';

@JsonSerializable()
class Banquets5 {
  const Banquets5({required this.taken, required this.refunded});

  factory Banquets5.fromJson(Map<String, Object?> json) =>
      _$Banquets5FromJson(json);

  final Taken10 taken;
  final Refunded11 refunded;

  Map<String, Object?> toJson() => _$Banquets5ToJson(this);
}
