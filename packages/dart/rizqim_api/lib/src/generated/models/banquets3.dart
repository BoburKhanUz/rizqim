// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken6.dart';
import 'refunded7.dart';

part 'banquets3.g.dart';

@JsonSerializable()
class Banquets3 {
  const Banquets3({required this.taken, required this.refunded});

  factory Banquets3.fromJson(Map<String, Object?> json) =>
      _$Banquets3FromJson(json);

  final Taken6 taken;
  final Refunded7 refunded;

  Map<String, Object?> toJson() => _$Banquets3ToJson(this);
}
