// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'unsigned_tills.dart';

part 'data162.g.dart';

@JsonSerializable()
class Data162 {
  const Data162({
    required this.enforce,
    required this.signingTills,
    required this.unsignedTills,
  });

  factory Data162.fromJson(Map<String, Object?> json) =>
      _$Data162FromJson(json);

  final bool enforce;
  @JsonKey(name: 'signing_tills')
  final int signingTills;
  @JsonKey(name: 'unsigned_tills')
  final List<UnsignedTills> unsignedTills;

  Map<String, Object?> toJson() => _$Data162ToJson(this);
}
