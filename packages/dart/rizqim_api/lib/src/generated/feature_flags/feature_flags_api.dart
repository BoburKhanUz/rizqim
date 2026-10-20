// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_features_response.dart';

part 'feature_flags_api.g.dart';

@RestApi()
abstract class FeatureFlagsApi {
  factory FeatureFlagsApi(Dio dio, {String? baseUrl}) = _FeatureFlagsApi;

  /// Feature flags.
  ///
  /// Flags (Laravel Pennant) as the signed-in user's store sees them: a map of flag name to on/off. Clients treat a.
  /// missing flag as off.
  @GET('/v1/features')
  Future<GetV1FeaturesResponse> featureIndex();
}
