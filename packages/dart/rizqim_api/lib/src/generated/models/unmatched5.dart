// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bank5.dart';
import 'rizqim5.dart';

part 'unmatched5.g.dart';

@JsonSerializable()
class Unmatched5 {
  const Unmatched5({required this.bank, required this.rizqim});

  factory Unmatched5.fromJson(Map<String, Object?> json) =>
      _$Unmatched5FromJson(json);

  final List<Bank5> bank;
  final List<Rizqim5> rizqim;

  Map<String, Object?> toJson() => _$Unmatched5ToJson(this);
}
