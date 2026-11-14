// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken4.dart';
import 'refunded5.dart';

part 'banquets2.g.dart';

@JsonSerializable()
class Banquets2 {
  const Banquets2({required this.taken, required this.refunded});

  factory Banquets2.fromJson(Map<String, Object?> json) =>
      _$Banquets2FromJson(json);

  final Taken4 taken;
  final Refunded5 refunded;

  Map<String, Object?> toJson() => _$Banquets2ToJson(this);
}
