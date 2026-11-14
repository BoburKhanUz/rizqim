// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'taken2.dart';
import 'refunded3.dart';

part 'banquets.g.dart';

@JsonSerializable()
class Banquets {
  const Banquets({required this.taken, required this.refunded});

  factory Banquets.fromJson(Map<String, Object?> json) =>
      _$BanquetsFromJson(json);

  final Taken2 taken;
  final Refunded3 refunded;

  Map<String, Object?> toJson() => _$BanquetsToJson(this);
}
