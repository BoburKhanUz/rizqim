// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'stopped.g.dart';

@JsonSerializable()
class Stopped {
  const Stopped({
    required this.products,
    required this.variants,
    required this.modifiers,
  });

  factory Stopped.fromJson(Map<String, Object?> json) =>
      _$StoppedFromJson(json);

  final List<int> products;
  final List<int> variants;
  final List<int> modifiers;

  Map<String, Object?> toJson() => _$StoppedToJson(this);
}
